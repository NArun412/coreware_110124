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

$GLOBALS['gPageCode'] = "EVENTREGISTRATIONREPORT";
require_once "shared/startup.inc";

class EventRegistrationReportPage extends Page implements BackgroundReport {

	private static function sortRegistrations($a, $b) {
		$sortKeyA = $a['locations']['description'] . "|" . $a['locations']['location_id'] . "|" . $a['event_types']['description'] . "|" . $a['event_types']['event_type_id'] . "|" .
			$a['events']['description'] . "|" . date("Y-m-d", strtotime($a['events']['start_date'])) . "|" . $a['events']['event_id'] . "|" . $a['last_name'] . "|" . $a['first_name'];
		$sortKeyB = $b['locations']['description'] . "|" . $b['locations']['location_id'] . "|" . $b['event_types']['description'] . "|" . $b['event_types']['event_type_id'] . "|" .
			$b['events']['description'] . "|" . date("Y-m-d", strtotime($a['events']['start_date'])) . "|" . $b['events']['event_id'] . "|" . $b['last_name'] . "|" . $b['first_name'];
		if ($sortKeyA == $sortKeyB) {
			return 0;
		}

		return ($sortKeyA > $sortKeyB ? 1 : -1);
	}

	private static function sortRegistrationsByDate($a, $b) {
		$sortKeyA = date("Y-m-d", strtotime($a['events']['start_date']))
			. "|" . $a['event_types']['description'] . "|" . $a['event_types']['event_type_id'] . "|" . $a['events']['description']
			. "|" . $a['events']['event_id'] . "|" . $a['last_name'] . "|" . $a['first_name'];
		$sortKeyB = date("Y-m-d", strtotime($b['events']['start_date']))
			. "|" . $b['event_types']['description'] . "|" . $b['event_types']['event_type_id'] . "|" . $b['events']['description']
			. "|" . $b['events']['event_id'] . "|" . $b['last_name'] . "|" . $b['first_name'];
		if ($sortKeyA == $sortKeyB) {
			return 0;
		}

		return ($sortKeyA > $sortKeyB ? 1 : -1);
	}

	private static function sortRegistrationsByLocationDate($a, $b) {
		$sortKeyA = $a['locations']['description'] . "|" . $a['locations']['location_id']
			. "|" . date("Y-m-d", strtotime($a['events']['start_date']))
			. "|" . $a['event_types']['description'] . "|" . $a['event_types']['event_type_id'] . "|" . $a['events']['description']
			. "|" . $a['events']['event_id'] . "|" . $a['last_name'] . "|" . $a['first_name'];
		$sortKeyB = $b['locations']['description'] . "|" . $b['locations']['location_id']
			. "|" . date("Y-m-d", strtotime($b['events']['start_date']))
			. "|" . $b['event_types']['description'] . "|" . $b['event_types']['event_type_id'] . "|" . $b['events']['description']
			. "|" . $b['events']['event_id'] . "|" . $b['last_name'] . "|" . $b['first_name'];
		if ($sortKeyA == $sortKeyB) {
			return 0;
		}

		return ($sortKeyA > $sortKeyB ? 1 : -1);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$returnArray = self::getReportContent();
				if (array_key_exists("report_export", $returnArray)) {
					if (is_array($returnArray['export_headers'])) {
						foreach ($returnArray['export_headers'] as $thisHeader) {
							header($thisHeader);
						}
					}
					echo $returnArray['report_export'];
				} else {
					echo jsonEncode($returnArray);
				}
				exit;
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		processPresetDates($_POST['preset_dates'], "event_date_from", "event_date_to", true);

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['event_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "event_id in (select event_id from events where start_date >= ?)";
			$parameters[] = makeDateParameter($_POST['event_date_from']);
		}
		if (!empty($_POST['event_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "event_id in (select event_id from events where start_date <= ?)";
			$parameters[] = makeDateParameter($_POST['event_date_to']);
		}
		if (!empty($_POST['event_date_from']) && !empty($_POST['event_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Event date is between " . date("m/d/Y", strtotime($_POST['event_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['event_date_to']));
		} else {
			if (!empty($_POST['event_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Event date is on or after " . date("m/d/Y", strtotime($_POST['event_date_from']));
			} else {
				if (!empty($_POST['event_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Event date is on or before " . date("m/d/Y", strtotime($_POST['event_date_to']));
				}
			}
		}

		if (!empty($_POST['location_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "event_id in (select event_id from events where location_id = ?)";
			$parameters[] = $_POST['location_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Location is " . getReadFieldFromId("description", "locations", "location_id", $_POST['location_id']);
		}

		if (!empty($_POST['event_type_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "event_id in (select event_id from events where event_type_id = ?)";
			$parameters[] = $_POST['event_type_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Event Type is " . getReadFieldFromId("description", "event_types", "event_type_id", $_POST['event_type_id']);
		}

		if (!empty($_POST['custom_fields'])) {
			$customFieldArray = explode(",", $_POST['custom_fields']);
		} else {
			$customFieldArray = array();
		}
		$customFieldHeaders = array();
		foreach ($customFieldArray as $customFieldId) {
			$customField = CustomField::getCustomField($customFieldId);
			$customFieldHeaders[] = $customField->getFormLabel();
		}
		if (!empty($_POST['event_registration_custom_fields'])) {
			$eventRegistrationCustomFieldArray = explode(",", $_POST['event_registration_custom_fields']);
		} else {
			$eventRegistrationCustomFieldArray = array();
		}
		if (!empty($_POST['contact_categories'])) {
			$contactCategoryIdArray = explode(",", $_POST['contact_categories']);
		} else {
			$contactCategoryIdArray = array();
		}
		foreach ($eventRegistrationCustomFieldArray as $customFieldId) {
			$customField = CustomField::getCustomField($customFieldId);
			$customFieldHeaders[] = $customField->getFormLabel();
		}
		$customFieldArray = array_merge($customFieldArray, $eventRegistrationCustomFieldArray);

		$exportReport = $_POST['report_type'] == "csv";
		$detailReport = $_POST['report_type'] == "detail";
		ob_start();
		$eventRegistrationCustomFieldTypeId = getReadFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "EVENT_REGISTRATIONS");

        $headers = array("Location", "Event Type", "Event Name", "Event Date", "Start Time", "Last Facility Use");
        if($_POST['include_facilities']) {
            $headers[] = "Reserved Facilities";
        }
        $headers[] = "Name";
        $headers[] = "Email";
        $headers[] = "Phone";

        $paymentMethods = array();
        if($_POST['include_account']) {
            $paymentMethodResult = executeReadQuery("select * from payment_methods where client_id = ?", $GLOBALS['gClientId']);
            while($paymentMethodRow = getNextRow($paymentMethodResult)) {
                $paymentMethods[$paymentMethodRow['payment_method_id']] = $paymentMethodRow['description'];
            }
            $headers[] = "Payment Method on file";
        }

        $eventTypeRequirements = array();
        if($_POST['include_certifications']) {
            $certificationResult = executeReadQuery("select * from event_type_requirements where event_type_id in (select event_type_id from event_types where client_id = ?)",$GLOBALS['gClientId']);
            while($certificationRow = getNextRow($certificationResult)) {
                $eventTypeRequirements[$certificationRow['event_type_id']][] = $certificationRow['certification_type_id'];
            }
            $headers[] = "Pre-requisites";
        }

        if($_POST['include_addons']) {
            $headers[] = "Registration addons";
        }

        $locations = array();
        $locationSet = executeReadQuery("select * from locations where client_id = ? and location_id in (select location_id from events)", $GLOBALS['gClientId']);
        while($locationRow = getNextRow($locationSet)) {
            $locations[$locationRow['location_id']] = $locationRow;
        }
        freeResult($locationSet);

        $eventTypes = array();
        $eventTypeSet = executeReadQuery("select * from event_types where client_id = ?", $GLOBALS['gClientId']);
        while($eventTypeRow = getNextRow($eventTypeSet)) {
            $eventTypes[$eventTypeRow['event_type_id']] = $eventTypeRow;
        }
        freeResult($eventTypeSet);

        $eventFacilities = array();
        if($_POST['include_facilities']) {
            $facilitiesSet = executeReadQuery("select distinct event_id, description from event_facilities join facilities using (facility_id) where event_id in (select event_id from events where events.client_id = ?"
                . (!empty($whereStatement) ? " and " . $whereStatement : "") . ") order by event_id", $parameters);
            while ($facilitiesRow = getNextRow($facilitiesSet)) {
                if (empty($eventFacilities[$facilitiesRow['event_id']])) {
                    $eventFacilities[$facilitiesRow['event_id']] = $facilitiesRow['description'];
                } else {
                    $eventFacilities[$facilitiesRow['event_id']] .= ", " . $facilitiesRow['description'];
                }
            }
            freeResult($facilitiesSet);
        }

        $registrations = array();
        $eventRows = array();

		$resultSet = executeReadQuery("select *,event_registrants.contact_id as registrant_id,"
			. " (select min(hour) from event_facilities where event_facilities.event_id = events.event_id) as start_time"
			. " from events left join event_registrants using (event_id) where events.client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by event_id", $parameters);
		while ($row = getNextRow($resultSet)) {
		    if (!array_key_exists($row['event_id'],$eventRows)) {
			    $eventRow = getReadRowFromId("events", "event_id", $row['event_id']);
			    $endSet = executeReadQuery("select date_needed,max(hour) from event_facilities where event_id = ? group by date_needed order by date_needed desc", $row['event_id']);
			    if ($endRow = getNextRow($endSet)) {
				    $eventRow['last_facility_use'] = date("m/d/Y", strtotime($endRow['date_needed'])) . " " . Events::getDisplayTime($endRow['max(hour)'],true);
			    }
			    $eventRows[$row['event_id']] = $eventRow;
		    }
		    $row['events'] = $eventRows[$row['event_id']];
			$row['start_time'] = Events::getDisplayTime($row['start_time']);
			$row['locations'] =  $locations[$row['events']['location_id']];
			$row['event_types'] = $eventTypes[$row['events']['event_type_id']];
            $row['facilities'] = $eventFacilities[$row['event_id']];
			if (!empty($row['registrant_id'])) {
				$row['contacts'] = getReadRowFromId('contacts', 'contact_id', $row['registrant_id']);
                if($_POST['include_account']) {
                    $accountRow = getReadRowFromId("accounts", "contact_id", $row['registrant_id'], "inactive = 0 and expiration_date > current_date");
                    if(!empty($accountRow)) {
                        $row['account'] = $paymentMethods[$accountRow['payment_method_id']] . " - " . substr($accountRow['account_number'], -4);
                    } else {
                        $row['account'] = "NONE";
                    }
                }
                if($_POST['include_certifications']) {
                    $row['certifications'] = "";
                    if(!empty($eventTypeRequirements[$row['event_type_id']])) {
                        foreach ($eventTypeRequirements[$row['event_type_id']] as $thisCertificationTypeId) {
                            $certificationRow = getReadRowFromId('certification_types', 'certification_type_id', $thisCertificationTypeId);
                            $contactCertificationRow = getReadRowFromId("contact_certifications", "contact_id", $row['registrant_id'], "certification_type_id = ?", $thisCertificationTypeId);
                            if (empty($contactCertificationRow) || strtotime($contactCertificationRow['expiration_date']) < time()) {
                                $row['certifications'] .= (empty($row['certifications']) ? "" : ", ") . $certificationRow['description'] . " NOT completed";
                            } else {
                                $row['certifications'] .= (empty($row['certifications']) ? "" : ", ") . $certificationRow['description'] . " completed on " . date("m/d/Y", strtotime($contactCertificationRow['date_issued']));
                            }
                        }
                    }
                    $row['certifications'] = $row['certifications'] ?: "N/A";
                }
                if($_POST['include_addons']) {
                    $orderItemId = getReadFieldFromId("order_item_id", "order_items", "order_id", $row['order_id'], "product_id = ?", $row['product_id']);
                    $addonSet = executeReadQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ? order by sort_order", $orderItemId);
                    $row['addons'] = "";
                    while ($addonRow = getNextRow($addonSet)) {
                        $salePrice = ($addonRow['quantity'] <= 1 ? $addonRow['sale_price'] : $addonRow['sale_price'] * $addonRow['quantity']);
                        $row['addons'] .= (empty($row['addons']) ? "" : "\n") . $addonRow['description'] . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")") . " - $" . number_format($salePrice, 2, ".", "");
                    }
                }
            }
			$registrations[] = $row;
		}
		if ($_POST['sort_by'] == "location_date") {
			usort($registrations, array(static::class, "sortRegistrationsByLocationDate"));
		} else if ($_POST['sort_by'] == "date") {
			usort($registrations, array(static::class, "sortRegistrationsByDate"));
		} else {
			usort($registrations, array(static::class, "sortRegistrations"));
		}
		foreach ($registrations as $index => $row) {
			$attendeeCounts = Events::getAttendeeCounts($row['event_id']);
			$registrations[$index]['attendee_counts'] = $attendeeCounts;
			$percentFull = ($attendeeCounts['attendees'] == 0 ? 0 : $attendeeCounts['registrants'] * 100 / $attendeeCounts['attendees']);
			$registrations[$index]['percent_full'] = $percentFull;
		}
		foreach ($registrations as $index => $row) {
			if (!empty($_POST['percent_full']) && $row['percent_full'] >= $_POST['percent_full']) {
				unset($registrations[$index]);
			}
		}
		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"eventregistrations.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "eventregistrations.csv";

			echo createCsvRow(array_merge($headers, $customFieldHeaders));
			foreach ($registrations as $index => $row) {
				$registrantName = 'None';
				$registrantEmail = '';
				$registrantPhone = '';
				if (!empty($row['registrant_id'])) {
					$registrantName = getDisplayName($row['registrant_id']);
					$registrantEmail = $row['contacts']['email_address'];
					$phoneResults = executeReadQuery("select distinct concat_ws('-',phone_number,description) as full_phone_number from phone_numbers where contact_id = ? limit 2", $row['registrant_id']);
					while ($phoneRow = getNextRow($phoneResults)) {
						$registrantPhone .= (empty($registrantPhone) ? "" : ",") . $phoneRow['full_phone_number'];
					}
					freeResult($phoneResults);
				}
				$dataArray = array($row['locations']['description'], $row['event_types']['description'], $row['events']['description'],
					date("m/d/Y", strtotime($row['events']['start_date'])), $row['start_time'], $row['events']['last_facility_use']);
                if($_POST['include_facilities']) {
                    $dataArray[] = $row['facilities'];
                }
                $dataArray[] = $registrantName;
                $dataArray[] = $registrantEmail;
                $dataArray[] = $registrantPhone;
                if($_POST['include_account']) {
                    $dataArray[] = $row['account'];
                }
                if($_POST['include_certifications']) {
                    $dataArray[] = $row['certifications'];
                }
                if($_POST['include_addons']) {
                    $dataArray[] = $row['addons'];
                }

				foreach ($customFieldArray as $customFieldId) {
					$customField = CustomField::getCustomField($customFieldId);
					$customFieldTypeId = getReadFieldFromId("custom_field_type_id", "custom_fields", "custom_field_id", $customFieldId);
					if ($customFieldTypeId == $eventRegistrationCustomFieldTypeId) {
						$customFieldDataId = getReadFieldFromId("custom_field_data_id", "custom_field_data", "primary_identifier",
							$row['event_registrant_id'], "custom_field_id = ?", $customFieldId);
						if (empty($customFieldDataId)) {
                            $dataArray[] = "";
                            continue;
						} else {
							$customField->setPrimaryIdentifier($row['event_registrant_id']);
						}
					} else {
						$customField->setPrimaryIdentifier($row['registrant_id']);
					}
					$dataArray[] = trim($customField->getDisplayData());
				}
				echo createCsvRow($dataArray);
			}
			$returnArray['report_export'] = ob_get_clean();
			return $returnArray;
		}

		$returnArray['report_title'] = "Event Registrations " . ($detailReport ? "Details" : "Summary") . " Report";
		?>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <table class='grid-table'>
			<?php
			if ($detailReport) {
				?>
                <tr>
                    <th>Location</th>
                    <th>Event Type</th>
                    <th>Event Name</th>
                    <th>Event Date</th>
                    <th>Start Time</th>
                    <th>Last Facility Use</th>
                    <?= ($_POST['include_facilities'] ? "<th>" . "Reserved Facilities" . "</th>" : "") ?>
                    <th>Name</th>
                    <th>Order ID</th>
					<?php
                    if($_POST['include_account']) {
                        echo "<th>" . "Payment Method on file" . "</th>";
                    }
                    if($_POST['include_certifications']) {
                        echo "<th>" . "Pre-requisites" . "</th>";
                    }
                    if($_POST['include_addons']) {
                        echo "<th>" . "Registration Addons" . "</th>";
                    }
					foreach ($contactCategoryIdArray as $categoryId) {
						echo "<th>" . htmlText(getReadFieldFromId("description", "categories", "category_id", $categoryId)) . "</th>";
					}
					foreach ($customFieldArray as $customFieldId) {
						$customField = CustomField::getCustomField($customFieldId);
						$customFieldTypeId = getReadFieldFromId("custom_field_type_id", "custom_fields", "custom_field_id", $customFieldId);
						echo "<th>" . htmlText($customField->getFormLabel()) . "</th>";
					}
					?>
                </tr>
			<?php } else { ?>
                <tr>
                    <th>Location</th>
                    <th>Event Type</th>
                    <th>Event Name</th>
                    <th>Event Date</th>
                    <th>Start Time</th>
                    <th>Last Facility Use</th>
                    <th class="align-right">Registrations/Max</th>
                </tr>
				<?php
			}
			$lastLocationId = "";
			$lastEventTypeId = "";
			$lastEventId = "";
			$saveLocationId = "";
			$saveEventTypeId = "";
			$saveEventId = "";
			$saveAttendees = 0;
			$saveStartTime = "";
			$saveLastFacilityUse = "";
			$totalCount = 0;
			$grandTotal = 0;
            $extraColumns = (empty($_POST['include_facilities']) ? 0 : 1) + (empty($_POST['include_account']) ? 0 : 1) + (empty($_POST['include_certifications']) ? 0 : 1)
                + (empty($_POST['include_addons']) ? 0 : 1) + count($customFieldArray) + count($contactCategoryIdArray);
			foreach ($registrations as $row) {
				if (empty($row['registrant_id'])) {
					$registrantInfo = 'None';
				} else {
					$registrantPhone = '';
					$phoneResults = executeReadQuery("select distinct concat_ws('-',phone_number,description) as full_phone_number from phone_numbers where contact_id = ? limit 2", $row['registrant_id']);
					while ($phoneRow = getNextRow($phoneResults)) {
						$registrantPhone .= (empty($registrantPhone) ? "" : ",") . $phoneRow['full_phone_number'];
					}
					freeResult($phoneResults);
					$registrantInfo = getDisplayName($row['registrant_id'])
						. (empty($row['contacts']['email_address']) ? "" : ", " . $row['contacts']['email_address'])
						. (empty($registrantPhone) ? "" : ", " . $registrantPhone);
				}
				if ($saveEventId != $row['event_id']) {
					if (!empty($saveEventId)) {
						if ($detailReport) {
							?>
                            <tr>
                                <td></td>
                                <td class='highlighted-text'>Total</td>
                                <td class='highlighted-text'><?= htmlText(getReadFieldFromId("description", "events", "event_id", $saveEventId)) ?></td>
                                <td colspan='<?= 5 + $extraColumns ?>' class='highlighted-text align-right'><?= $totalCount . "/" . $saveAttendees ?></td>
                            </tr>
							<?php
						} else {
							?>
                            <tr>
                                <td><?= htmlText($lastLocationId == $saveLocationId ? "" : getReadFieldFromId("description", "locations", "location_id", $saveLocationId)) ?></td>
                                <td><?= htmlText($lastEventTypeId == $saveEventTypeId ? "" : getReadFieldFromId("description", "event_types", "event_type_id", $saveEventTypeId)) ?></td>
                                <td><?= htmlText(getReadFieldFromId("description", "events", "event_id", $saveEventId)) ?></td>
                                <td><?= date("m/d/Y", strtotime(getReadFieldFromId("start_date", "events", "event_id", $saveEventId))) ?></td>
                                <td><?= $saveStartTime ?></td>
                                <td><?= $saveLastFacilityUse ?></td>
                                <td class='align-right'><?= $totalCount . "/" . $saveAttendees ?></td>
                            </tr>
							<?php
							$lastLocationId = $saveLocationId;
							$lastEventTypeId = $saveEventTypeId;
							$lastEventId = $saveEventId;
						}
					}
					$saveLocationId = $row['locations']['location_id'];
					$saveEventTypeId = $row['events']['event_type_id'];
					$saveEventId = $row['events']['event_id'];
					$saveStartTime = $row['start_time'];
                    $saveLastFacilityUse = $row['events']['last_facility_use'];
					$saveAttendees = $row['attendee_counts']['attendees'];
					$grandTotal += $totalCount;
					$totalCount = 0;
				}
				if ($detailReport) {
					?>
                    <tr>
                        <td><?= htmlText($saveLocationId == $lastLocationId ? "" : $row['locations']['description']) ?></td>
                        <td><?= htmlText($saveEventTypeId == $lastEventTypeId ? "" : $row['event_types']['description']) ?></td>
                        <td><?= htmlText($saveEventId == $lastEventId ? "" : $row['events']['description']) ?></td>
                        <td><?= htmlText($saveEventId == $lastEventId ? "" : date("m/d/Y", strtotime($row['events']['start_date']))) ?></td>
                        <td><?= htmlText($saveEventId == $lastEventId ? "" : $row['start_time']) ?></td>
                        <td><?= htmlText($saveEventId == $lastEventId ? "" : $row['events']['last_facility_use']) ?></td>
	                    <?= ($_POST['include_facilities'] ? "<td>" . htmlText($saveEventId == $lastEventId ? "" : $row['facilities']) . "</td>" : "") ?>
                        <td><?= htmlText($registrantInfo) ?></td>
                        <td><?= $row['order_id'] ?></td>
                        <?= ($_POST['include_account'] ? "<td>" . htmlText($row['account']) . "</td>" : "") ?>
                        <?= ($_POST['include_certifications'] ? "<td>" . htmlText($row['certifications']) . "</td>" : "") ?>
                        <?= ($_POST['include_addons'] ? "<td>" . htmlText(str_replace("\n", "<br>", $row['addons'])) . "</td>" : "") ?>
						<?php
						foreach ($contactCategoryIdArray as $categoryId) {
							$contactCategoryId = getReadFieldFromId("contact_category_id", "contact_categories", "contact_id", $row['registrant_id'], "category_id = ?", $categoryId);
							echo "<td>" . (empty($contactCategoryId) ? "" : "YES") . "</td>";
						}
						foreach ($customFieldArray as $customFieldId) {
							$customField = CustomField::getCustomField($customFieldId);
							$customFieldTypeId = getReadFieldFromId("custom_field_type_id", "custom_fields", "custom_field_id", $customFieldId);
							if ($customFieldTypeId == $eventRegistrationCustomFieldTypeId) {
								$customFieldDataId = getReadFieldFromId("custom_field_data_id", "custom_field_data", "primary_identifier",
									$row['event_registrant_id'], "custom_field_id = ?", $customFieldId);
								if (empty($customFieldDataId)) {
									echo "<td></td>";
								} else {
									$customField->setPrimaryIdentifier($row['event_registrant_id']);
									echo "<td>" . $customField->getDisplayData(false, array("custom_table" => true)) . "</td>";
                                    if (!empty($customField->getColumnControl("additional_registrants"))) {
	                                    $customFieldData = getReadFieldFromId("text_data", "custom_field_data", "primary_identifier",
		                                    $row['event_registrant_id'], "custom_field_id = ?", $customFieldId);
                                        if (startsWith($customFieldData,"[{")) {
	                                        $fieldValueArray = json_decode($customFieldData, true);
                                            if (is_array($fieldValueArray)) {
	                                            $totalCount += count($fieldValueArray);
                                            }
                                        }
                                    }
								}
							} else {
								$customField->setPrimaryIdentifier($row['registrant_id']);
								echo "<td>" . $customField->getDisplayData(false, array("custom_table" => true)) . "</td>";
							}
						}
						?>
                    </tr>
					<?php
					$lastLocationId = $saveLocationId;
					$lastEventTypeId = $saveEventTypeId;
					$lastEventId = $saveEventId;
				}
				if (!empty($row['registrant_id'])) {
					$totalCount++;
				}
			}
			if (!empty($saveEventId)) {
				if ($detailReport) {
					?>
                    <tr>
                        <td></td>
                        <td class="highlighted-text">Total</td>
                        <td class="highlighted-text"><?= htmlText(getReadFieldFromId("description", "events", "event_id", $saveEventId)) ?></td>
                        <td colspan='<?= 5 + $extraColumns ?>' class='highlighted-text align-right'><?= $totalCount . " (out of " . $row['attendee_counts']['attendees'] . ")" ?></td>
                    </tr>
					<?php
				} else {
					?>
                    <tr>
                        <td><?= htmlText($lastLocationId == $saveLocationId ? "" : getReadFieldFromId("description", "locations", "location_id", $saveLocationId)) ?></td>
                        <td><?= htmlText($lastEventTypeId == $saveEventTypeId ? "" : getReadFieldFromId("description", "event_types", "event_type_id", $saveEventTypeId)) ?></td>
                        <td><?= htmlText(getReadFieldFromId("description", "events", "event_id", $saveEventId)) ?></td>
                        <td><?= date("m/d/Y", strtotime(getReadFieldFromId("start_date", "events", "event_id", $saveEventId))) ?></td>
                        <td><?= $row['start_time'] ?></td>
                        <td><?= $row['events']['last_facility_use'] ?></td>
                        <td class='align-right'><?= $totalCount . " (out of " . $row['attendee_counts']['attendees'] . ")" ?></td>
                    </tr>
					<?php
				}
			}
			$grandTotal += $totalCount;
			?>
            <tr>
                <td colspan="6" class="align-right">Total Registrations</td>
                <td colspan="<?= 2 + $extraColumns ?>" class='align-right'><?= $grandTotal ?></td>
            </tr>
        </table>
		<?php
		$returnArray['report_content'] = ob_get_clean();
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                        <option value="csv">CSV Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_location_id_row">
                    <label for="location_id">Location</label>
                    <select tabindex='10' id="location_id" name="location_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from locations where product_distributor_id is null and user_location = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_event_type_id_row">
                    <label for="event_type_id">Event Type</label>
                    <select tabindex='10' id="event_type_id" name="event_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from event_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['event_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_sort_by_row">
                    <label for="sort_by">Sort by</label>
                    <select tabindex='10' id="sort_by" name="sort_by">
                        <option value="">Location, Event Type</option>
                        <option value="location_date">Location, Date</option>
                        <option value="date">Date</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_event_date_row">
                    <label for="event_date_from">Event Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="event_date_from" name="event_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="event_date_to" name="event_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_percent_full_row">
                    <label for="percent_full">Less than X Percent Full</label>
                    <input type='text' size='6' tabindex='10' class='align-right validate[custom[integer]],max[100],min[0]' id="percent_full" name="percent_full">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$customFieldControl = new DataColumn("custom_fields");
				$customFieldControl->setControlValue("data_type", "custom");
				$customFieldControl->setControlValue("control_class", "MultiSelect");
				$customFieldControl->setControlValue("control_table", "custom_fields");
				$customFieldControl->setControlValue("links_table", "contacts");
				$customFieldControl->setControlValue("primary_table", "contacts");
				$customFieldControl->setControlValue("choice_where", "custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')");
				$customControl = new MultipleSelect($customFieldControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_custom_fields_row">
                    <label for="custom_fields">Contact Custom Fields</label>
					<?= $customControl->getControl() ?>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$eventCustomFieldControl = new DataColumn("event_registration_custom_fields");
				$eventCustomFieldControl->setControlValue("data_type", "custom");
				$eventCustomFieldControl->setControlValue("control_class", "MultiSelect");
				$eventCustomFieldControl->setControlValue("control_table", "custom_fields");
				$eventCustomFieldControl->setControlValue("links_table", "event_registrants");
				$eventCustomFieldControl->setControlValue("primary_table", "event_registrants");
				$eventCustomFieldControl->setControlValue("choice_where", "custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'EVENT_REGISTRATIONS')");
				$eventCustomControl = new MultipleSelect($eventCustomFieldControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_event_registration_custom_fields_row">
                    <label for="event_registration_custom_fields">Event Registration Custom Fields</label>
					<?= $eventCustomControl->getControl() ?>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$categoryCustomFieldControl = new DataColumn("contact_categories");
				$categoryCustomFieldControl->setControlValue("data_type", "custom");
				$categoryCustomFieldControl->setControlValue("control_class", "MultiSelect");
				$categoryCustomFieldControl->setControlValue("control_table", "categories");
				$categoryCustomFieldControl->setControlValue("links_table", "contact_categories");
				$categoryCustomFieldControl->setControlValue("primary_table", "contact_categories");
				$categoryCustomControl = new MultipleSelect($categoryCustomFieldControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line" id="_contact_categories_row">
                    <label for="contact_categories">Contact Categories</label>
					<?= $categoryCustomControl->getControl() ?>
                    <div class='basic-form-line-messages'><span class="help-label">Include flag as to whether the Contact is in these categories in report</span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_facilities_row">
                    <input type="checkbox" tabindex="10" id="include_facilities" name="include_facilities" value="1"><label class="checkbox-label" for="include_facilities">Show facilities reserved for the event.</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_account_row">
                    <input type="checkbox" tabindex="10" id="include_account" name="include_account" value="1"><label class="checkbox-label" for="include_account">Show whether student has a saved payment method on file.</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_certifications_row">
                    <input type="checkbox" tabindex="10" id="include_certifications" name="include_certifications" value="1"><label class="checkbox-label" for="include_certifications">Show whether student has completed prerequisites for the event.</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_addons_row">
                    <input type="checkbox" tabindex="10" id="include_addons" name="include_addons" value="1"><label class="checkbox-label" for="include_addons">Show student addons in event registration.</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>



                <?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="refresh_button">Refresh</button>
            <button id="new_parameters_button">Search Again</button>
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
        </div>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                const $pdfForm = $("#_pdf_form");
                $pdfForm.html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("eventregistrations.pdf");
                $pdfForm.append($(input));
                $pdfForm.attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                const $reportForm = $("#_report_form");
                if ($reportForm.validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "csv") {
                        $reportForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $reportForm.serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({scrollTop: 0}, 600);
                            }
                        });
                    }
                }
                return false;
            });
            $(document).on("tap click", "#new_parameters_button", function () {
                $("#report_parameters").show();
                $("#_report_title").hide();
                $("#_report_content").hide();
                $("#_button_row").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #report_parameters {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            #_report_content {
                display: none;
            }

            #_report_content table td {
                font-size: .9rem;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
        <style id="_printable_style">
            #_report_content {
                width: auto;
                display: block;
            }

            #_report_title {
                width: auto;
                display: block;
            }
        </style>
		<?php
	}
}

$pageObject = new EventRegistrationReportPage();
$pageObject->displayPage();
