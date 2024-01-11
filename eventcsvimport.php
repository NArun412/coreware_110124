<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "EVENTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class EventCsvImportPage extends Page {

	var $iErrorMessages = array();
    private $iValidEventFields = array("event_id", "event_type_code", "description", "detailed_description", "location_code", "dates", "start_date", "start_time", "end_date", "end_time", "link_name",
    "link_url", "attendees", "facilities", "internal_use_only");
    private $iValidProductFields = array("product_code", "product_category", "product_tags", "product_sku", "product_price", "product_expiration_days", "product_not_taxable", "product_tax_rate",
    "product_internal_use_only", "product_cart_maximum", "product_addon_set");
    private $iShowDetailedErrors = false;


    function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "remove_import":
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "csv_import_id", $_GET['csv_import_id']);
				if (empty($csvImportId)) {
					$returnArray['error_message'] = "Invalid CSV Import";
					ajaxResponse($returnArray);
					break;
				}
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "events", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to events";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

                $tempTableName = "temp_product_ids_" . getRandomString(10);
                executeQuery("create table " . $tempTableName . "(product_id int not null,primary key (product_id))");
                executeQuery("insert into " . $tempTableName . "(product_id) (select product_id from events where event_id in (select primary_identifier from csv_import_details where csv_import_id = ?))", $csvImportId);

                $deleteSet = executeQuery("delete from event_facilities where event_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to events");

                $deleteSet = executeQuery("delete from events where event_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to events");

                # delete event products
                $imageIds = array();
                $resultSet = executeQuery("select image_id from products where image_id is not null and product_id in (select product_id from " . $tempTableName . ")");
                while ($row = getNextRow($resultSet)) {
                    $imageIds[] = $row['image_id'];
                }
                $resultSet = executeQuery("select image_id from product_images where product_id in (select product_id from " . $tempTableName . ")");
                while ($row = getNextRow($resultSet)) {
                    $imageIds[] = $row['image_id'];
                }

                $deleteSet = executeQuery("delete from product_images where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product images");

                $deleteSet = executeQuery("delete from product_group_variant_choices where product_group_variant_id in (select product_group_variant_id from product_group_variants where product_id in (select product_id from " . $tempTableName . "))");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product_group_variant_choices");

                $deleteSet = executeQuery("delete from product_group_variants where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product_group_variants");

                $deleteSet = executeQuery("delete from product_addons where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product_addons");

                $deleteSet = executeQuery("delete from product_category_links where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product category links");

                $deleteSet = executeQuery("delete from product_search_word_values where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product_search_word_values");

                $deleteSet = executeQuery("delete from product_prices where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product_prices");

                $deleteSet = executeQuery("delete from product_sale_prices where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product_sale_prices");

                $deleteSet = executeQuery("delete from product_data where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: product data");

                $deleteSet = executeQuery("delete from products where product_id in (select product_id from " . $tempTableName . ")");
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: products");

                executeQuery("drop table " . $tempTableName);

                if (!empty($imageIds)) {
                    $deleteSet = executeQuery("delete from images where image_id in (" . implode(",", $imageIds) . ") and client_id = ?", $GLOBALS['gClientId']);
                    $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to event registration products: images");
                }

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to events");

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray);

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);
				break;
			case "import_csv":
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fieldValue);
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportId)) {
					$returnArray['error_message'] = "This file has already been imported.";
					ajaxResponse($returnArray);
					break;
				}
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

                $allValidFields = array_merge($this->iValidEventFields, $this->iValidProductFields);
				$requiredFields = array("event_type_code", "start_date|dates");
				$numericFields = array("event_id", "attendees", "product_price", "product_expiration_days");
				$dateTimeFields = array("start_date", "start_time", "end_date", "end_time");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$this->iErrorMessages = array();
				# parse file and check for invalid fields
				while ($csvData = fgetcsv($openFile)) {
					if ($count == 0) {
						foreach ($csvData as $thisName) {
							$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
						}
						$invalidFields = "";
						foreach ($fieldNames as $fieldName) {
							if (!in_array($fieldName, $allValidFields)) {
								$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
							}
						}
						if (!empty($invalidFields)) {
							$this->addErrorMessage("Invalid fields in CSV: " . $invalidFields);
							$this->addErrorMessage("Valid fields are: " . implode(", ", $allValidFields));
						}
					} else {
						$fieldData = array();
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
                            if(in_array($thisFieldName,$numericFields)) {
                                $fieldData[$thisFieldName] = str_replace(["$",","],"",trim($thisData));
                            } else {
                                $fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
                            }
						}
                        if(!empty(array_filter($fieldData))) {
                            $importRecords[] = $fieldData;
                        }
					}
					$count++;
				}
				fclose($openFile);
				# check for required fields and invalid data
				$locations = array();
				$eventTypes = array();
				$facilities = array();
				$linkNames = array();
                $productCodes = array();
                $productCategories = array();
                $productTags = array();
                $productAddonSets = array();
                $taxRates = array();
				foreach ($importRecords as $index => $thisRecord) {
                    $missingFields = "";
                    foreach ($requiredFields as $thisField) {
                        if (strpos($thisField, "|") !== false) {
                            $alternateRequiredFields = explode("|", $thisField);
                            $found = false;
                            foreach ($alternateRequiredFields as $thisAlternate) {
                                $found = $found ?: !empty($thisRecord[$thisAlternate]);
                            }
                            if (!$found) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . str_replace("|", " or ", $thisField);
                            }
                        } else {
                            if (empty($thisRecord[$thisField])) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
                            }
                        }
                    }
                    if (!empty($missingFields)) {
                        $this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
                    }

                    foreach ($numericFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
                        }
                    }
                    foreach ($dateTimeFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName]) && strtotime($thisRecord[$fieldName]) == false) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be a valid date or time: " . $thisRecord[$fieldName]);
                        }
                    }
                    if (!empty($thisRecord['dates'])) {
                        $dateParts = explode(" to ", $thisRecord['dates']);
                        if (count($dateParts) != 2 || strtotime($dateParts[0]) == false || strtotime($dateParts[1]) == false) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Dates must include a valid start date/time and a valid end date/time separated by ' to ': " . $thisRecord['dates']);
                        }
                    }
                    $eventTypeRow = $eventTypes[$thisRecord['event_type_code']];
                    if (empty($eventTypeRow)) {
                        $eventTypeRow = getRowFromId("event_types", "event_type_code", makeCode($thisRecord['event_type_code']));
                    }
                    if (empty($eventTypeRow)) {
                        $eventTypeRow = getRowFromId("event_types", "description", $thisRecord['event_type_code']);
                    }
                    if (empty($eventTypeRow)) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": Event Type does not exist: " . $thisRecord['event_type_code']);
                    } else {
                        $eventTypes[$thisRecord['event_type_code']] = $eventTypeRow;
                    }
                    if(empty([$thisRecord['attendees']]) && empty($eventTypeRow['attendees'])) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": attendees is required.");
                    }
                    if (!empty($thisRecord['location_code'])) {
                        $locationId = $locations[$thisRecord['location_code']];
                        if (empty($locationId)) {
                            $locationId = getFieldFromId("location_id", "locations", "location_code", $thisRecord['location_code']);
                        }
                        if (empty($locationId)) {
                            $locationId = getFieldFromId("location_id", "locations", "description", $thisRecord['location_code']);
                        }
                        if (empty($locationId)) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": Location does not exist: " . $thisRecord['location_code']);
                        } else {
                            $locations[$thisRecord['location_code']] = $locationId;
                        }
                    }
                    if (!empty($thisRecord['facilities'])) {
                        foreach (array_map("trim", explode("|", $thisRecord['facilities'])) as $thisFacility) {
                            $facilityId = $facilities[$thisFacility];
                            if (empty($facilityId)) {
                                $facilityId = getFieldFromId("facility_id", "facilities", "description", $thisFacility);
                            }
                            if (empty($facilityId)) {
                                $this->addErrorMessage("Line " . ($index + 2) . ": Facility does not exist: " . $thisFacility);
                            } else {
                                $facilities[$thisFacility] = $facilityId;
                            }
                        }
                    }
                    # check link names
                    if (!empty($thisRecord['dates'])) {
                        $dateParts = explode(" to ", $thisRecord['dates']);
                        $startDate = date("Y-m-d", strtotime($dateParts[0]));
                        $startTime = date("H:i", strtotime($dateParts[0]));
                    } else {
                        $startDate = date("Y-m-d", strtotime($thisRecord['start_date']));
                        $startTime = date("H:i", strtotime($thisRecord['start_time']));
                    }
                    $linkName = $thisRecord['link_name'] ?: $thisRecord['description'] . " " . $startDate . " " . $startTime . (empty($thisRecord['location_code']) ? "" : " " . $thisRecord['location_code']);
                    $existingLinkName = getFieldFromId("link_name", "events", "link_name", $linkName);
                    if (empty($existingLinkName)) {
                        $existingLinkName = $linkNames[$linkName];
                    }
                    if (!empty($existingLinkName) && empty($_POST['skip_duplicate_links'])) {
                        $this->addErrorMessage("Line " . ($index + 2) . ": Link_name must be unique; this value already exists: " . $linkName);
                    } else {
                        $linkNames[$linkName] = true;
                    }
                    $createProduct = false;
                    foreach ($this->iValidProductFields as $productField) {
                        if (!empty($thisRecord[$productField])) {
                            $createProduct = true;
                            break;
                        }
                    }
                    if ($createProduct) {
                        if (empty($thisRecord["product_category"])) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": If product is specified, product_category is required.");
                        }
                        if (strlen($thisRecord["product_price"]) == 0 && empty($eventTypeRow['price'])) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": If product is specified, product_price is required.");
                        }
                        if (!empty($thisRecord['product_category'])) {
                            if (!array_key_exists($thisRecord['product_category'], $productCategories)) {
                                $productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", makeCode($thisRecord['product_category']));
                                if (empty($productCategoryId)) {
                                    $productCategories[$thisRecord['product_category']] = "";
                                } else {
                                    $productCategories[$thisRecord['product_category']] = $productCategoryId;
                                }
                            }
                        }
                        if (!empty($thisRecord['product_tags'])) {
                            $tags = explode("|",$thisRecord['product_tags']);
                            foreach($tags as $thisTag) {
                                if (!array_key_exists($thisTag, $productTags)) {
                                    $productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", makeCode($thisTag));
                                    $productTagId = $productTagId ?: getFieldFromId("product_tag_id", "product_tags", "description", $thisTag);
                                    if (empty($productTagId)) {
                                        $this->addErrorMessage("Line " . ($index + 2) . ": Product tag not found: " . $thisTag . " (multiple tags must be delimited with |)");
                                    } else {
                                        $productTags[$thisTag] = $productTagId;
                                    }
                                }
                            }
                        }

                        if (!empty($thisRecord['product_addon_set'])) {
                            foreach (array_map("trim", explode("|", $thisRecord['product_addon_set'])) as $thisAddonSet) {
                                $maxQuantity = 0; // if quantity is not specified, do not override addon set value
                                $addonSetDescription = $thisAddonSet;
                                if(strpos($thisAddonSet,":") !== false) {
                                    $parts = explode(":", $thisAddonSet);
                                    $lastPart = array_pop($parts);
                                    if (is_numeric($lastPart)) {
                                        $maxQuantity = $lastPart;
                                        $addonSetDescription = implode(":", $parts);
                                    }
                                }
                                if (!array_key_exists($thisAddonSet, $productAddonSets)) {
                                    $productAddonSetId = getFieldFromId("product_addon_set_id", "product_addon_sets", "description", $addonSetDescription);
                                    if (empty($productAddonSetId)) {
                                        $this->addErrorMessage("Line " . ($index + 2) . ": Product Addon set not found: " . $thisAddonSet);
                                    } else {
                                        $productAddonSets[$thisAddonSet] = array("product_addon_set_id"=>$productAddonSetId, "maximum_quantity"=>$maxQuantity);
                                    }
                                }
                            }
                        }

                        # check for duplicate product_codes
                        if (!empty($thisRecord['product_code'])) {
                            $productCode = makeCode(trim($thisRecord['product_code']));
                            $existingProductCode = getFieldFromId("product_code", "products", "product_code", $productCode);
                            if (empty($existingProductCode)) {
                                $existingProductCode = $productCodes[$thisRecord['product_code']];
                            }
                            if (!empty($existingProductCode) && empty($_POST['skip_duplicate_product_codes'])) {
                                $this->addErrorMessage("Line " . ($index + 2) . ": Product_code must be unique; this value already exists: " . $thisRecord['product_code']);
                            } else {
                                $productCodes[$thisRecord['product_code']] = true;
                            }
                        }
                        if (!empty($thisRecord['product_tax_rate'])) {
                            if (!array_key_exists($thisRecord['product_tax_rate'], $taxRates)) {
                                $taxRateId = getFieldFromId("tax_rate_id", "tax_rates", "tax_rate_code", makeCode($thisRecord['product_tax_rate']));
                                if (empty($taxRateId)) {
                                    $taxRateId = getFieldFromId("tax_rate_id", "tax_rates", "description", $thisRecord['product_tax_rate']);
                                }
                                if (empty($taxRateId)) {
                                    $this->addErrorMessage("Line " . ($index + 2) . ": Product Tax Rate not found: " . $thisRecord['product_tax_rate']);
                                } else {
                                    $taxRates[$thisRecord['product_tax_rate']] = $taxRateId;
                                }
                            }
                        }
                    }
				}

				if (!empty($this->iErrorMessages)) {
					$returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
					foreach ($this->iErrorMessages as $thisMessage => $count) {
						$returnArray['import_error'] .= "<p>" . ($count > 1 ? $count . ": " : "") . $thisMessage . "</p>";
					}
					ajaxResponse($returnArray);
					break;
				}

				# do import
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'events',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

                foreach ($productCategories as $thisProductCategory => $productCategoryId) {
                    if (empty($productCategoryId)) {
                        $insertSet = executeQuery("insert into product_categories (client_id, product_category_code, description) values (?,?,?)",
                            $GLOBALS['gClientId'], makeCode($thisProductCategory), $thisProductCategory);
                        $this->checkSqlError($insertSet, $returnArray);
                        $productCategories[$thisProductCategory] = $insertSet['insert_id'];
                    }
                }
                $productTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "EVENT_REGISTRATION");
                if (empty($productTypeId)) {
                    $resultSet = executeQuery("insert into product_types (client_id,product_type_code,description) values (?,'EVENT_REGISTRATION','Event Registration')", $GLOBALS['gClientId']);
                    $productTypeId = $resultSet['insert_id'];
                }
                $salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
                if (empty($salePriceTypeId)) {
                    $resultSet = executeQuery("insert into product_price_types (client_id,product_price_type_code,description) values (?,'SALE_PRICE','Sale Price')", $GLOBALS['gClientId']);
                    $salePriceTypeId = $resultSet['insert_id'];
                }


                $productCategoryLinksDataTable = new DataTable("product_category_links");
                $productCategoryLinksDataTable->setSaveOnlyPresent(true);
                $productTagLinksDataTable = new DataTable("product_tag_links");
                $productTagLinksDataTable->setSaveOnlyPresent(true);

                $linkNames = array();
                $newEventIds = array();
                $productCodes = array();
                $productLinkNames = array();
				$insertCount = 0;
				$updateCount = 0;
                $this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));
                foreach ($importRecords as $index => $thisRecord) {
					$eventTypeRow = $eventTypes[$thisRecord['event_type_code']];
                    $eventTypeId = $eventTypeRow['event_type_id'];
					if (!empty($thisRecord['dates'])) {
						$dateParts = explode(" to ", $thisRecord['dates']);
						$startDate = date("Y-m-d", strtotime($dateParts[0]));
						$startTime = date("H:i", strtotime($dateParts[0]));
						$endDate = "";
						if (strlen(trim($dateParts[1])) > 8) { // try to catch situations where only an end time is given (strtotime will return today's date)
							$endDate = date("Y-m-d", strtotime($dateParts[1]));
						}
						if ($endDate == $startDate) {
							$endDate = "";
						}
						$endTime = date("H:i", strtotime($dateParts[1]));
					} else {
						$startDate = date("Y-m-d", strtotime($thisRecord['start_date']));
						$endDate = date("Y-m-d", strtotime($thisRecord['end_date']));
						$startTime = date("H:i", strtotime($thisRecord['start_time']));
						$endTime = date("H:i", strtotime($thisRecord['end_time']));
					}
					if (strtotime($startDate) == false) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = "Invalid start date found: " . jsonEncode($thisRecord);
						ajaxResponse($returnArray);
						break;
					}

					$startHour = $this->getHour($startTime);
					$endHour = $this->getHour($endTime, true);

					$locationId = $locations[$thisRecord['location_code']];
					$internalUseOnly = (!empty($thisRecord['internal_use_only']) && !in_array(strtolower($thisRecord['internal_use_only']), ["false", "no"]) ? 1 : 0);
					$facilityIds = array();
					foreach (array_map("trim", explode("|", $thisRecord['facilities'])) as $thisFacility) {
						if (!empty($thisFacility)) {
							$facilityIds[] = $facilities[$thisFacility];
						}
					}
					$linkName = $thisRecord['link_name'] ?: $thisRecord['description'] . " " . $startDate . " " . $startTime . (empty($thisRecord['location_code']) ? "" : " " . $thisRecord['location_code']);
					$linkName = makeCode($linkName, array("use_dash" => true, "lowercase" => true));
					if (!empty($linkNames[$linkName]) && $_POST['skip_duplicate_links']) {
						$linkName = "";
					} else {
						$linkNames[$linkName] = true;
					}

					# check for existing event
                    $eventId = $thisRecord['event_id'];
                    if(empty($eventId) && !empty($linkName)) {
                        $eventId = getFieldFromId("event_id", "events", "link_name", $linkName);
                    }
                    if(empty($eventId)) {
                        $existingEventParameters = array($startHour, $eventTypeId, $startDate);
                        if (!empty($locationId)) {
                            $existingEventWhere = " and location_id = ?";
                            $existingEventParameters[] = $locationId;
                        } else {
                            $existingEventWhere = " and location_id is null";
                        }
                        $existingEventResult = executeQuery("select event_id from events where event_id in (select event_id from event_facilities group by event_id having min(hour) = ?) and event_type_id = ? and start_date = ?" . $existingEventWhere,
                            $existingEventParameters);
                        if ($existingEventRow = getNextRow($existingEventResult)) {
                            $eventId = $existingEventRow['event_id'];
                        }
                        if (empty($eventId)) { // check for existing event with no facilities
                            array_shift($existingEventParameters); // remove start hour as a parameter
                            $existingEventResult = executeQuery("select event_id from events where event_id not in (select event_id from event_facilities) and event_type_id = ? and start_date = ?" . $existingEventWhere,
                                $existingEventParameters);
                            if ($existingEventRow = getNextRow($existingEventResult)) {
                                $eventId = $existingEventRow['event_id'];
                            }
                        }
                    }
                    if(empty($eventId) && !empty($thisRecord['product_code'])) {
                        $eventId = getFieldFromId("event_id", "events", "product_id",
                            getFieldFromId("product_id", "products", "product_code", makeCode($thisRecord['product_code'])));
                    }
					if (!empty($eventId)) {
						$startHour = $this->getHour($startTime);
						$eventFacilityId = getFieldFromId("event_facility_id", "event_facilities", "event_id", $eventId, "hour = ?", $startHour);
						if (empty($eventFacilityId) && !empty(getFieldFromId("event_facility_id", "event_facilities", "event_id", $eventId))) {
							$eventId = false;
						}
					}

					if (empty($eventId) || in_array($eventId, $newEventIds)) {
                        $pastEvent = (strtotime($endDate ?: $startDate) < time() ? 1 : 0);
                        $resultSet = executeQuery("insert into events (client_id, description, detailed_description, event_type_id, location_id, start_date, end_date, attendees, link_name, link_url, finalize, date_created, internal_use_only) " .
							" values (?,?,?,?,?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $thisRecord['description'], $thisRecord['detailed_description'], $eventTypeId, $locationId,
							$startDate, $endDate, $thisRecord['attendees'] ?: $eventTypeRow['attendees'], $linkName, $thisRecord['link_url'], $pastEvent, date("Y-m-d"), $internalUseOnly);
                        $this->checkSqlError($resultSet, $returnArray);
						$eventId = $resultSet['insert_id'];
                        $newEventIds[] = $eventId;
						$insertCount++;
						$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $eventId);
                        $this->checkSqlError($insertSet, $returnArray);
					} else {
                        $eventRow = getRowFromId("events", "event_id", $eventId);
                        $thisRecord['description'] = $thisRecord['description'] ?: $eventRow['description'];
                        $thisRecord['detailed_description'] = $thisRecord['detailed_description'] ?: $eventRow['detailed_description'];
                        $locationId = $locationId ?: $eventRow['location_id'];
                        $endDate = $endDate ?: $eventRow['end_date'];
                        $thisRecord['attendees'] = $thisRecord['attendees'] ?: $eventRow['attendees'];
						$resultSet = executeQuery("update events set description = ?, detailed_description = ?, location_id = ?, end_date = ?, attendees = ?, link_name = ?, link_url = ?, internal_use_only = ? where event_id = ?",
							$thisRecord['description'], $thisRecord['detailed_description'], $locationId, $endDate, $thisRecord['attendees'], $linkName, $thisRecord['link_url'], $internalUseOnly, $eventId);
                        $this->checkSqlError($resultSet, $returnArray);
						$updateCount++;
					}
                    if(strlen($thisRecord['facilities']) > 0) {
                        executeQuery("delete from event_facilities where event_id = ?", $eventId);
                        foreach ($facilityIds as $facilityId) {
                            $valuesQuery = "";
                            $date = $startDate;
                            while ($date <= ($endDate ?: $startDate)) {
                                for ($hour = $startHour; $hour < $endHour; $hour += .25) {
                                    $valuesQuery .= (empty($valuesQuery) ? "" : ",") . sprintf("(%s,%s,'%s',%s)", $eventId, $facilityId, $date, $hour);
                                }
                                $date = date("Y-m-d", strtotime($date . " +1 day"));
                            }
                            if (!empty($valuesQuery)) {
                                $resultSet = executeQuery("insert into event_facilities (event_id, facility_id, date_needed, hour) values " . $valuesQuery);
                                $this->checkSqlError($resultSet, $returnArray);
                            }
                        }
                    }
                    # create registration product if requested
                    if(!empty($thisRecord['product_category'])) {
                        $productCode = makeCode(trim($thisRecord['product_code']));
                        if (!empty($productCodes[$productCode]) && $_POST['skip_duplicate_product_codes']) {
                            $productCode = "";
                        } else {
                            $productCodes[$productCode] = true;
                        }
                        $productCode = $productCode ?: "EVENT_" . date("mdY", strtotime($startDate)) . "_" . strtoupper(getRandomString(10));
                        $displayTime = Events::getDisplayTime($startHour);
                        $imageId = getFieldFromId("image_id", "event_types", "event_type_id", $eventTypeId);
                        $locationName = getFieldFromId("description", "locations", "location_id", $locationId);
                        $linkName = makeCode("registration for " . $thisRecord['description'] . " on " . $startDate . " at " . str_replace(":", "", $startTime) . (empty($locationName) ? "" : " " . $locationName),
                            array("use_dash" => true, "lowercase" => true));
                        if (!empty($productLinkNames[$linkName]) && $_POST['skip_duplicate_links']) {
                            $linkName = "";
                        } else {
                            $productLinkNames[$linkName] = true;
                        }
                        $productPrice = (strlen($thisRecord['product_price']) > 0 ? $thisRecord['product_price'] : $eventTypeRow['price']);
                        $productId = getFieldFromId("product_id", "events", "event_id", $eventId);
                        if(empty($productId)) {
                            $insertSet = executeQuery("insert into products (client_id,product_code,description,detailed_description,image_id,product_type_id,link_name,cart_maximum," .
                                "list_price,tax_rate_id,date_created,time_changed,expiration_date,not_taxable,reindex,virtual_product,non_inventory_item,internal_use_only) " .
                                "values (?,?,?,?,?,?,?,?,?,?,now(),now(),?,?,1,1,1,?)", $GLOBALS['gClientId'], $productCode,
                                $thisRecord['description'] . ", " . date("m/d/Y", strtotime($startDate)) . " " . $displayTime . " registration", $thisRecord['detailed_description'],
                                $imageId, $productTypeId, $linkName, $thisRecord['product_cart_maximum'], $productPrice, $taxRates[$thisRecord['product_tax_rate']],
                                (empty($thisRecord['product_expiration_days']) ? "" : date("Y-m-d", strtotime($startDate . "-" . $thisRecord['product_expiration_days'] . " days"))),
                                (empty($thisRecord['product_not_taxable']) ? 0 : 1), (empty($thisRecord['product_internal_use_only']) ? 0 : 1));
                            $this->checkSqlError($insertSet, $returnArray);
                            $productId = $insertSet['insert_id'];
                            if(!empty($thisRecord['product_sku'])) {
                                $insertSet = executeQuery("insert into product_data (client_id, product_id, manufacturer_sku) values (?,?,?)",
                                $GLOBALS['gClientId'], $productId, $thisRecord['product_sku']);
                            }
                            $this->checkSqlError($insertSet, $returnArray);

                            $eventTypeProductId = getFieldFromId("product_id", "event_types", "event_type_id", $eventTypeId);
	                        if (!empty($eventTypeProductId)) {
		                        $resultSet = executeQuery("select * from related_products where product_id = ?",$eventTypeProductId);
		                        while ($row = getNextRow($resultSet)) {
			                        executeQuery("insert into related_products (product_id,associated_product_id,related_product_type_id) values (?,?,?)",$productId,$row['associated_product_id'],$row['related_product_type_id']);
		                        }
	                        }

	                        $productCategoryLinksDataTable->saveRecord(array("name_values"=>array("product_category_id"=>$productCategories[$thisRecord['product_category']],"product_id"=>$productId)));
                            if(!empty($thisRecord['product_tags'])) {
                                $tags = explode("|",$thisRecord['product_tags']);
                                foreach($tags as $thisTag) {
                                    $productTagId = $productTags[makeCode($thisTag)] ?: $productTags[$thisTag];
                                    $productTagLinksDataTable->saveRecord(array("name_values"=>array("product_tag_id"=>$productTagId,"product_id"=>$productId)));
                                }
                            }
                            executeQuery("update events set product_id = ? where event_id = ?", $productId, $eventId);
                            if(!empty($thisRecord['product_addon_set'])) {
                                foreach (array_map("trim", explode("|", $thisRecord['product_addon_set'])) as $thisAddonSet) {
                                    $addonSet = executeQuery("select * from product_addon_set_entries where product_addon_set_id = ?", $productAddonSets[$thisAddonSet]['product_addon_set_id']);
                                    while ($addonRow = getNextRow($addonSet)) {
                                        $maxQuantity = $productAddonSets[$thisAddonSet]['maximum_quantity'] ?: $addonRow['maximum_quantity'];
                                        executeQuery("insert into product_addons (product_id, description, group_description, manufacturer_sku, form_definition_id, maximum_quantity, sale_price, sort_order) values (?,?,?,?,?,?,?,?)",
                                            $productId, $addonRow['description'], $addonRow['group_description'], $addonRow['manufacturer_sku'], $addonRow['form_definition_id'], $maxQuantity, $addonRow['sale_price'], $addonRow['sort_order']);
                                    }
                                }
                            }
                        } else {
                            $productRow = getRowFromId("products", "product_id", $productId);
                            $thisRecord['description'] = $thisRecord['description'] ?: $productRow['description'];
                            $thisRecord['detailed_description'] = $thisRecord['detailed_description'] ?: $productRow['detailed_description'];
                            $imageId = $imageId ?: $productRow['image_id'];
                            $thisRecord['product_cart_maximum'] = $thisRecord['product_cart_maximum'] ?: $productRow['cart_maximum'];
                            $taxRateId = $taxRates[$thisRecord['product_tax_rate']] ?: $productRow['tax_rate_id'];
                            $expirationDate = (empty($thisRecord['product_expiration_days']) ? $productRow['expiration_date'] : date("Y-m-d", strtotime($startDate . "-" . $thisRecord['product_expiration_days'] . " days")));
                            $notTaxable = (isset($thisRecord['product_not_taxable']) ? (empty($thisRecord['product_not_taxable']) ? 0 : 1) : $productRow['not_taxable']);
                            $internalUseOnly = (isset($thisRecord['product_internal_use_only']) ? (empty($thisRecord['product_internal_use_only']) ? 0 : 1) : $productRow['internal_use_only']);
                            $resultSet = executeQuery("update products set description = ?, detailed_description = ?, image_id = ?, link_name = ?, cart_maximum = ?, list_price = ?," .
                                "tax_rate_id = ?, time_changed = now(), expiration_date = ?, not_taxable = ?,reindex = 1,virtual_product = 1, non_inventory_item = 1, internal_use_only = ? " .
                                "where product_id = ?",$thisRecord['description'] . ", " . date("m/d/Y", strtotime($startDate)) . " " . $displayTime . " registration",
                                $thisRecord['detailed_description'], $imageId, $linkName, $thisRecord['product_cart_maximum'], $productPrice,
                                $taxRateId, $expirationDate, $notTaxable, $internalUseOnly, $productId);
                            $this->checkSqlError($resultSet, $returnArray);
                            if(array_key_exists("product_sku", $thisRecord)) {
                                executeQuery("update product_data set manufacturer_sku = ? where product_id = ?", $thisRecord['product_sku'], $productId);
                            }
                            executeQuery("delete from product_prices where product_id = ?",$productId);
                            executeQuery("delete from product_category_links where product_id = ?", $productId);
                            executeQuery("delete from product_tag_links where product_id = ?", $productId);
	                        $productCategoryLinksDataTable->saveRecord(array("name_values"=>array("product_category_id"=>$productCategories[$thisRecord['product_category']],"product_id"=>$productId)));
                            if(!empty($thisRecord['product_tags'])) {
                                $tags = explode("|",$thisRecord['product_tags']);
                                $productTagLinksDataTable = new DataTable("product_tag_links");
                                foreach($tags as $thisTag) {
                                    $productTagId = $productTags[makeCode($thisTag)] ?: $productTags[$thisTag];
                                    $productTagLinksDataTable->saveRecord(array("name_values"=>array("product_tag_id"=>$productTagId,"product_id"=>$productId)));
                                }
                            }
                            if(!empty($thisRecord['product_addon_set'])) {
                                executeQuery("delete ignore from product_addons where product_id = ?", $productId);
                                foreach (array_map("trim", explode("|", $thisRecord['product_addon_set'])) as $thisAddonSet) {
                                    $addonSet = executeQuery("select * from product_addon_set_entries where product_addon_set_id = ?", $productAddonSets[$thisAddonSet]['product_addon_set_id']);
                                    while ($addonRow = getNextRow($addonSet)) {
                                        // product addon form does not behave correctly if the same form definition is attached to two addons for the same product
                                        $existingAddonId = getFieldFromId("product_addon_id", "product_addons", "product_id", $productId, "form_definition_id = ?",
                                            $addonRow['form_definition_id']);
                                        $maxQuantity = $productAddonSets[$thisAddonSet]['maximum_quantity'] ?: $addonRow['maximum_quantity'];
                                        if(empty($existingAddonId)) {
                                            executeQuery("insert into product_addons (product_id, description, group_description, manufacturer_sku, form_definition_id, maximum_quantity, sale_price, sort_order) values (?,?,?,?,?,?,?,?)",
                                                $productId, $addonRow['description'], $addonRow['group_description'], $addonRow['manufacturer_sku'], $addonRow['form_definition_id'], $maxQuantity, $addonRow['sale_price'], $addonRow['sort_order']);
                                        } else {
                                            executeQuery("update product_addons set description = ?, group_description = ?, manufacturer_sku = ?, maximum_quantity = ?, sale_price = ?, sort_order = ? where product_addon_id = ?",
                                                $addonRow['description'], $addonRow['group_description'], $addonRow['manufacturer_sku'], $maxQuantity, $addonRow['sale_price'], $addonRow['sort_order'], $existingAddonId);
                                        }
                                    }
                                }
                            }
                        }
                    }
				}

                executeQuery("update background_processes set run_immediately = 1 where background_process_code = 'EVENT_PRODUCT_VARIANTS'");
                $GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['response'] = "<p>" . $insertCount . " events imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " existing events updated.</p>";
				ajaxResponse($returnArray);
				break;
		}
	}

	function addErrorMessage($errorMessage) {
		if (array_key_exists($errorMessage, $this->iErrorMessages)) {
			$this->iErrorMessages[$errorMessage]++;
		} else {
			$this->iErrorMessages[$errorMessage] = 1;
		}
	}

    function checkSqlError($resultSet, &$returnArray, $errorMessage = "") {
        if (!empty($resultSet['sql_error'])) {
            if($this->iShowDetailedErrors) {
                $returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'];
            } else {
                $returnArray['error_message'] = $returnArray['import_error'] = $errorMessage ?: getSystemMessage("basic", $resultSet['sql_error']);
            }
            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
            ajaxResponse($returnArray);
        }
    }

    function getHour($timeString, $endTime = false, $increment = .25) {
        $timeParts = explode(":",$timeString);
        $minuteDivisor = 60 * $increment;
        $roundedOffMinute = $endTime ? ceil($timeParts[1] / $minuteDivisor) : floor($timeParts[1] / $minuteDivisor);
        return $timeParts[0] + ($roundedOffMinute * $increment);
    }

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description" name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_duplicate_links_row">
                    <input type="checkbox" tabindex="10" id="skip_duplicate_links" name="skip_duplicate_links" value="1"><label
                            class="checkbox-label" for="skip_duplicate_links">Leave link name blank if it is a duplicate (instead of failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_duplicate_product_codes_row">
                    <input type="checkbox" tabindex="10" id="skip_duplicate_product_codes" name="skip_duplicate_product_codes" value="1"><label
                            class="checkbox-label" for="skip_duplicate_product_codes">Generate random product code if it is a duplicate (instead of failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

                <div id="import_error"></div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th>Undo</th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'events' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = ($minutesSince < 120 || $GLOBALS['gDevelopmentServer']);
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row" data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".remove-import", function () {
                var csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 400,
                    title: 'Remove Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function(returnArray) {
                                if ("csv_import_id" in returnArray) {
                                    $("#csv_import_id_" + returnArray['csv_import_id']).remove();
                                }
                            });
                            $("#_confirm_undo_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_undo_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_submit_form", function () {
                if ($("#_submit_form").data("disabled") === "true") {
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    disableButtons($("#_submit_form"));
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }
                        if ("import_error" in returnArray) {
                            $("#import_error").html(returnArray['import_error']);
                        }
                        if ("response" in returnArray) {
                            $("#_form_div").html(returnArray['response']);
                        }
                        enableButtons($("#_submit_form"));
                    });
                }
                return false;
            });
            $(document).on("tap click", ".valid-fields-trigger", function () {
                $("#_valid_fields_dialog").dialog({
                    modal: true,
                    resizable: true,
                    width: 1000,
                    title: 'Valid Fields',
                    buttons: {
                        Close: function (event) {
                            $("#_valid_fields_dialog").dialog('close');
                        }
                    }
                });
            });
            $("#_valid_fields_dialog .accordion").accordion({
                active: false,
                heightStyle: "content",
                collapsible: true
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #import_error {
                color: rgb(192, 0, 0);
            }

            .remove-import {
                cursor: pointer;
            }
            #_valid_fields_dialog .ui-accordion-content {
                max-height: 200px;
            }

            #_valid_fields_dialog > ul {
                columns: 3;
                padding-bottom: 1rem;
            }

            #_valid_fields_dialog .ui-accordion ul {
                columns: 2;
            }

            #_valid_fields_dialog ul li {
                padding-right: 20px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_valid_fields_dialog" title="Valid Fields" class="dialog-box">
        <ul>
            <li><?= implode("</li><li>", array_merge($this->iValidEventFields,$this->iValidProductFields)) ?></li>
        </ul>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these events being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new EventCsvImportPage();
$pageObject->displayPage();
